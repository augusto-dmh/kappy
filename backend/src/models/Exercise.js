import { Model, DataTypes } from 'sequelize';

export default class Exercise extends Model {
  static init(sequelize) {
    super.init(
      {
        lessonId: {
          type: DataTypes.INTEGER,
          references: {
            model: 'lessons',
            key: 'id',
          },
        },
        question: {
          type: DataTypes.TEXT,
          allowNull: false,
        },
      },
      {
        sequelize,
        modelName: "exercise",
      }
    );
  }

  static associate(models) {
    this.belongsTo(models.Lesson, {
      foreignKey: 'lessonId',
      as: 'lesson',
    });
    this.hasMany(models.exerciseChoice, {
      foreignKey: 'exerciseId',
      as: 'choices',
    });
  }
}
