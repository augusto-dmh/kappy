import { Model, DataTypes } from 'sequelize';

export default class Lesson extends Model {
  static init(sequelize) {
    super.init(
      {
        name: {
          type: DataTypes.STRING,
          allowNull: false,
        },
        previousLesson: {
            type: DataTypes.INTEGER,
            allowNull: true,
            references: {
              model: 'lesson',
              key: 'id',
          }
        }
      },
      {
        sequelize,
        modelName: "lesson",
      }
    );
  }
  static associate(models) {
    this.hasMany(models.user, {
      foreignKey: 'checkpoint',
      as: 'users',
    });
    this.hasMany(models.exercise, {
      foreignKey: 'lessonId',
      as: 'exercises',
    });
    this.belongsTo(models.lesson, {
      foreignKey: 'previousLesson',
      as: 'previous',
    });
  }
}
