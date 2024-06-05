import { Model, DataTypes } from 'sequelize';

export default class ExerciseChoices extends Model {
  static init(sequelize) {
    super.init(
      {
        exerciseId: {
          type: DataTypes.INTEGER,
          references: {
            model: 'exercises',
            key: 'id',
          },
        },
        choiceId: {
          type: DataTypes.INTEGER,
          allowNull: false,
        },
        choiceText: {
          type: DataTypes.TEXT,
          allowNull: false,
        },
        isCorrect: {
          type: DataTypes.BOOLEAN,
          defaultValue: false,
        },
      },
      {
        sequelize,
        modelName: "exerciseChoice",
      }
    );
  }

  static associate(models) {
    this.belongsTo(models.exercise, {
      foreignKey: 'exerciseId',
      as: 'exercise',
    });
  }
}
